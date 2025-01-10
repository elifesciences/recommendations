elifePipeline {
    def commit
    DockerImage image
    stage 'Checkout', {
        checkout scm
        commit = elifeGitRevision()
    }

    node('containers-jenkins-plugin') {
        stage 'Build image', {
            checkout scm
            commit = elifeGitRevision()
            commitShort = elifeGitRevision().substring(0, 8)
            branch = sh(script: 'git rev-parse --abbrev-ref HEAD', returnStdout: true).trim()
            timestamp = sh(script: 'date --utc +%Y%m%d.%H%M', returnStdout: true).trim()
            dockerComposeBuild commit
        }

        stage 'Project tests', {
            dockerProjectTests 'recommendations', commit
        }

        elifeMainlineOnly {
            stage 'Push image', {
                image = DockerImage.elifesciences(this, "recommendations", commit).push()
                image.tag("${branch}-${commitShort}-${timestamp}").push()
            }
        }
    }

    elifeMainlineOnly {
        stage 'Deploy on continuumtest', {
            lock('recommendations--continuumtest') {
                builderDeployRevision 'recommendations--continuumtest', commit
                builderSmokeTests 'recommendations--continuumtest', '/srv/recommendations'
            }
        }

        stage 'Approval', {
            elifeGitMoveToBranch commit, 'approved'
            node('containers-jenkins-plugin') {
                image.pull().tag('approved').push()
            }
        }
    }
}
